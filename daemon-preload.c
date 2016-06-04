#define _GNU_SOURCE
#include <dlfcn.h>
#include <stdio.h>
#include <sys/time.h>
#include <stdlib.h>
#include <sys/timeb.h>

int diff;

int (*org_gettimeofday)(struct timeval *tp, void *tzp);
time_t (*org_time)(time_t *tloc);
int (*org_clock_gettime)(clockid_t clk_id, struct timespec *tp);

void
_initLib(void)
{
	org_gettimeofday =  dlsym(RTLD_NEXT, "gettimeofday");
	org_time =          dlsym(RTLD_NEXT, "time");
	org_clock_gettime = dlsym(RTLD_NEXT, "clock_gettime");

	int offset = 0;
	if (getenv("TIME") != NULL) {
		offset = atoi(getenv("TIME"));
//fprintf(stderr, "%s has set a custom offset\n", __FUNCTION__);
	}

	unsetenv("TIME");
	unsetenv("LD_PRELOAD");

	diff = org_time(NULL) - offset;
}

int gettimeofday(struct timeval *restrict tp, struct timezone *restrict tzp) {
	if (0 == diff)
		_initLib();

	int r = org_gettimeofday(tp, tzp);

	if (!r)
		return r;

	tp->tv_sec -= diff;
	return 0;
}

time_t time(time_t *t) {
	if (0 == diff)
		_initLib();

	time_t r = org_time(t) - diff;

	if (t)
		*t = r;

	return r;
}

int clock_gettime(clockid_t clk_id, struct timespec *tp) {
	if (0 == diff)
		_initLib();

	int r = org_clock_gettime(clk_id, tp);

	if (!r)
		return r;

	tp->tv_sec -= diff;
	return 0;
}

int ftime(struct timeb *tp) {
	if (0 == diff)
		_initLib();

	tp->time = org_time(NULL) - diff;

	return 0;
}
